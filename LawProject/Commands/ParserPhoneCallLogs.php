<?php

namespace App\Console\Commands;

use App\Helpers\PhoneCallLogsHelper;
use App\Models\Contact;
use App\Models\PhoneCallLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Facades\Text;

class ParserPhoneCallLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parser:phone-call-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parser Phone Call Logs';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Start parsing phone call logs');

        $token = '';
        $countRecords = 0;

        $startDate = date('Y-m-d', time() - 172800) . ' 00:00:00'; // - 2 days
        $endDate = date('Y-m-d') . ' 23:59:59';

        $accesData = PhoneCallLogsHelper::login();

        if (isset($accesData->access_token)) {
            $this->info('Token: ' . $accesData->access_token);
            $token = $accesData->access_token;
        } else {
            $this->info('Can\'t get access token');
            return;
        }

        if ($token) {
            $logsData = PhoneCallLogsHelper::getPhoneCallLog($startDate, $endDate, $token);

            if ($logsData && count($logsData) > 0) {
                foreach ($logsData as $log) {

                    $fromUser = strlen(Text::clearInteger($log->CdrR->orig_from_user)) == 11
                        ? Text::clearInteger($log->CdrR->orig_from_user)
                        : Text::clearInteger(PhoneCallLogsHelper::COUNTRY_DIALING_AMERICA_CODE . $log->CdrR->orig_from_user);

                    $toUser = strlen(Text::clearInteger($log->CdrR->orig_to_user)) == 11
                        ? Text::clearInteger($log->CdrR->orig_to_user)
                        : Text::clearInteger(PhoneCallLogsHelper::COUNTRY_DIALING_AMERICA_CODE . $log->CdrR->orig_to_user);

                    $contacts = Contact::where('primary', 1)
                        ->whereHas('phones', function ($query) use ($fromUser, $toUser) {
                            $query->where('value', $fromUser)
                                ->orwhere('value', $toUser);
                        })->get();

                    if ($contacts->count()) {
                        foreach ($contacts as $contact) {
                            $phoneCallLogExist = PhoneCallLog::where('cdr_id', $log->CdrR->id)->where('user_id', $contact->user_id)->first();

                            if ($contact && !$phoneCallLogExist) {

                                $phoneCallLog = PhoneCallLog::make([
                                    'user_id' => $contact->user_id,
                                    'cdr_id' => $log->CdrR->id,
                                    'orig_call_id' => $log->CdrR->orig_callid,
                                    'term_call_id' => $log->CdrR->term_callid,
                                    'time_start' => date('Y-m-d H:i:s', $log->CdrR->time_start),
                                    'time_answer' => $log->CdrR->time_answer ? date('Y-m-d H:i:s', $log->CdrR->time_answer) : null,
                                    'time_release' => date('Y-m-d H:i:s', $log->CdrR->time_release),
                                    'duration' => $log->duration,
                                    'time_talking' => $log->CdrR->time_talking,
                                    'from_number' => $fromUser,
                                    'to_number' => $toUser,
                                ]);

                                if ($phoneCallLog->save()) {
                                    $this->info('Cdr ' . $phoneCallLog->id . ' for user ' . $contact->user_id . ' saved');
                                    $countRecords++;
                                    $fileUrl = null;

                                    // GetFilePath and save file to the storage
                                    $recordLog = PhoneCallLogsHelper::getRecord($token, $phoneCallLog->orig_call_id, $phoneCallLog->term_call_id);
                                    $fileUrl = $recordLog->url ?? null;

                                    if (!$fileUrl) {
                                        $index = 'vm-' . $log->CdrR->by_callid . '.wav';
                                        if ($log->CdrR->term_sub) {
                                            $recordLog = PhoneCallLogsHelper::getVoiceMailRecord($token, $index, $log->CdrR->term_sub);
                                            $fileUrl = isset($recordLog->$index) && isset($recordLog->$index->remotepath) ? str_replace('amp;', '', $recordLog->$index->remotepath) : null;
                                        }
                                    }

                                    if ($fileUrl) {
                                        $file = file_get_contents($fileUrl);
                                        $fileName = $phoneCallLog->cdr_id . '_' . rand(100, 999) . '.wav';

                                        $pathParts = [
                                            config('common.aws_s3_work_folder'),
                                            PhoneCallLogsHelper::PHONE_CALL_LOG_FOLDER,
                                            $fileName,
                                        ];

                                        $cloudPath = implode(DIRECTORY_SEPARATOR, $pathParts);

                                        if (!Storage::disk('s3')->put($cloudPath, $file)) {
                                            $cloudPath = null;
                                        } else {
                                            $this->info('File was downloaded');
                                        }

                                        $phoneCallLog->file_path = $cloudPath;
                                        $phoneCallLog->save();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $this->info('Stop parsing. Count records: ' . $countRecords);
    }
}
