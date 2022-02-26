<?php

namespace Stanford\OnCoreIntegration;

use ExternalModules\ExternalModules;

/**
 * Class Protocols
 * @package Stanford\OnCoreIntegration
 * @property Users $user
 * @property array $onCoreProtocol
 */
class Protocols extends Entities
{
    /**
     * @var Users
     */
    private $user;

    /**
     * @var array
     */
    private $onCoreProtocol;

    /**
     * @var array
     */
    private $entityRecord;

    /**
     * @var Subjects
     */
    private $subjects;

    /**
     * @var array
     */
    private $fieldsMap;

    /**
     * @param $user
     * @param $reset
     */
    public function __construct($user, $redcapProjectId = '', $reset = false)
    {
        parent::__construct($reset);

        $this->setUser($user);

        // if protocol is initiated for specific REDCap project. then check if this ONCORE_PROTOCOL entity record exists and pull OnCore Protocol via  API
        if ($redcapProjectId) {
            $this->prepareProtocol($redcapProjectId);
        }
    }


    public function processSyncedRecords()
    {
        if (!$this->getEntityRecord()) {
            throw new \Exception('No REDCap Project linked to OnCore Protocol found.');
        }

        $redcapRecords = $this->getSubjects()->getRedcapProjectRecords();
//        if(!$redcapRecords){
//            throw new \Exception('Cant find recap records');
//        }

        $oncoreProtocolSubjects = $this->getSubjects()->getOnCoreProtocolSubjects($this->getEntityRecord()['oncore_protocol_id']);
//        if(!$oncoreProtocolSubjects){
//            throw new \Exception('Cant find oncore subjects');
//        }

        $fields = $this->getFieldsMap();

        if (!$fields) {
            throw new \Exception('Fields map is not defined.');
        }

        foreach ($oncoreProtocolSubjects as $subject) {
            $onCoreMrn = $subject['demographics']['mrn'];
            $redcapRecord = $this->getSubjects()->getREDCapRecordIdViaMRN($onCoreMrn, $this->getEntityRecord()['redcap_event_id'], $fields['mrn']);
            if ($redcapRecord) {
                $data = array(
                    'redcap_project_id' => $this->getEntityRecord()['redcap_project_id'],
                    'oncore_protocol_id' => $this->getEntityRecord()['oncore_protocol_id'],
                    'redcap_record_id' => $redcapRecord['id'],
                    'oncore_protocol_subject_id' => $subject['protocolSubjectId'],
                    'status' => $this->getSubjects()->determineSyncedRecordMatch($subject, $redcapRecord['record'], $fields)
                );
                // select oncore subject without redcap record
                $record = $this->getSubjects()->getLinkageRecord($this->getEntityRecord()['redcap_project_id'], $this->getEntityRecord()['oncore_protocol_id'], '', $subject['protocolSubjectId']);
                if ($record) {
                    $this->getSubjects()->updateLinkageRecord($record['id'], $data);
                } else {
                    //select redcap record without oncore subject
                    $record = $this->getSubjects()->getLinkageRecord($this->getEntityRecord()['redcap_project_id'], $this->getEntityRecord()['oncore_protocol_id'], $redcapRecord['id'], '');
                    if ($record) {
                        $this->getSubjects()->updateLinkageRecord($record['id'], $data);
                    } else {
                        $entity = $this->getSubjects()->create(OnCoreIntegration::ONCORE_REDCAP_RECORD_LINKAGE, $data);
                        if (!$entity) {
                            throw new \Exception(implode(',', $entity->errors));
                        }
                    }
                }
                // now remove redcap record from array
                unset($redcapRecords[$redcapRecord['id']]);
            } else {
                $record = $this->getSubjects()->getLinkageRecord($this->getEntityRecord()['redcap_project_id'], $this->getEntityRecord()['oncore_protocol_id'], '', $subject['protocolSubjectId']);
                // only insert if no record found
                if (!$record) {
                    // here OnCore subject does not exists on redcap
                    $data = array(
                        'redcap_project_id' => $this->getEntityRecord()['redcap_project_id'],
                        'oncore_protocol_id' => $this->getEntityRecord()['oncore_protocol_id'],
                        'redcap_record_id' => '',
                        'oncore_protocol_subject_id' => $subject['protocolSubjectId'],
                        'status' => OnCoreIntegration::RECORD_NOT_ON_REDCAP_BUT_ON_ONCORE
                    );
                    //TODO check if redcap record deleted.
                    $entity = $this->getSubjects()->create(OnCoreIntegration::ONCORE_REDCAP_RECORD_LINKAGE, $data);
                    if (!$entity) {
                        throw new \Exception(implode(',', $entity->errors));
                    }
                }
            }
        }

        // left redcap records on redcap but not on oncore
        foreach ($redcapRecords as $id => $redcapRecord) {
            $record = $this->getSubjects()->getLinkageRecord($this->getEntityRecord()['redcap_project_id'], $this->getEntityRecord()['oncore_protocol_id'], $id, '');

            if (!$record) {
                $data = array(
                    'redcap_project_id' => $this->getEntityRecord()['redcap_project_id'],
                    'oncore_protocol_id' => $this->getEntityRecord()['oncore_protocol_id'],
                    'redcap_record_id' => $id,
                    'oncore_protocol_subject_id' => '',
                    'status' => OnCoreIntegration::RECORD_ON_REDCAP_BUT_NOT_ON_ONCORE
                );
                $entity = $this->getSubjects()->create(OnCoreIntegration::ONCORE_REDCAP_RECORD_LINKAGE, $data);
                if (!$entity) {
                    throw new \Exception(implode(',', $entity->errors));
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getFieldsMap(): array
    {
        return json_decode(ExternalModules::getProjectSetting($this->getUser()->getPREFIX(), $this->getEntityRecord()['redcap_project_id'], OnCoreIntegration::REDCAP_ONCORE_FIELDS_MAPPING_NAME), true);
    }

    /**
     * this method will save fields map array into EM project settings.
     * @param array $fieldsMap
     * @return void
     */
    public function setFieldsMap(array $fieldsMap): void
    {
        // TODO
//        $test = array("subjectDemographicsId" => "subjectDemographicsId",
//            "subjectSource" => "subjectSource",
//            "mrn" => "mrn",
//            "lastName" => "lastName",
//            "firstName" => "firstName",
//            "middleName" => "middleName",
//            "suffix" => "suffix",
//            "birthDate" => "birthDate",
//            "approximateBirthDate" => "approximateBirthDate",
//            "birthDateNotAvailable" => "birthDateNotAvailable",
//            "expiredDate" => "expiredDate",
//            "approximateExpiredDate" => "approximateExpiredDate",
//            "lastDateKnownAlive" => "lastDateKnownAlive",
//            "ssn" => "ssn",
//            "gender" => "gender",
//            "ethnicity" => "ethnicity",
//            "race" => "race",
//            "subjectComments",
//            "additionalSubjectIds",
//            "streetAddress",
//            "addressLine2",
//            "city",
//            "state",
//            "zip",
//            "county",
//            "country",
//            "phoneNo",
//            "alternatePhoneNo",
//            "email");

        ExternalModules::setProjectSetting($this->getUser()->getPREFIX(), $this->getEntityRecord()['redcap_project_id'], OnCoreIntegration::REDCAP_ONCORE_FIELDS_MAPPING_NAME, json_encode($fieldsMap));
        $this->fieldsMap = $fieldsMap;
    }

    public function prepareProtocol($redcapProjectId)
    {
        $protocol = $this->getProtocolEntityRecord($redcapProjectId);
        if (!empty($protocol)) {
            $this->setEntityRecord($protocol);
            $this->setOnCoreProtocol($this->searchOnCoreProtocolsViaID($this->getEntityRecord()['oncore_protocol_id']));
            /**
             * if OnCore protocol found then prepare its subjects
             */
            $this->prepareProtocolSubjects();

            /**
             * get REDCap records for linked protocol.
             */
            $this->prepareProjectRecords();

        }
    }

    /**
     * this function will gather records for linked redcap project.
     * @return void
     */
    public function prepareProjectRecords()
    {
        $this->getSubjects()->setRedcapProjectRecords($this->getEntityRecord()['redcap_project_id'], $this->getEntityRecord()['redcap_project_id']);
    }

    /**
     * gather and save subjects for linked OnCore Protocol
     * @return void
     */
    public function prepareProtocolSubjects()
    {
        try {
            $this->setSubjects(new Subjects($this->getUser()));
            $this->getSubjects()->setOnCoreProtocolSubjects($this->getEntityRecord()['oncore_protocol_id']);
        } catch (\Exception $e) {
            // TODO exception handler
        }
    }

    public function isContactPartOfOnCoreProtocol($contactId)
    {
        try {
            //TODO can redcap user who is a contact can other redcap users?
            if (empty($this->getUser()->getOnCoreAdmin())) {

                throw new \Exception("Can not find a OnCore Admin");
            }
            if (empty($this->getOnCoreProtocol())) {
                throw new \Exception("No protocol found for current REDCap project.");
            }
            $jwt = $this->getUser()->getAccessToken();
            $response = $this->getUser()->getGuzzleClient()->get($this->getUser()->getApiURL() . $this->getUser()->getApiURN() . 'protocolStaff?protocolId=' . $this->getOnCoreProtocol()['protocolId'], [
                'debug' => false,
                'headers' => [
                    'Authorization' => "Bearer {$jwt}",
                ]
            ]);

            if ($response->getStatusCode() < 300) {
                $staffs = json_decode($response->getBody(), true);
                foreach ($staffs as $staff) {
                    if ($contactId == $staff['contactId']) {
                        return $staff;
                    }
                }
                return false;
            }
            return false;
        } catch (\Exception $e) {
            Entities::createException($e->getMessage());
            throw new \Exception($e->getMessage());

        }
    }

    public function searchOnCoreProtocolsViaID($protocolID)
    {
        try {
            $jwt = $this->getUser()->getAccessToken();
            $response = $this->getUser()->getGuzzleClient()->get($this->getUser()->getApiURL() . $this->getUser()->getApiURN() . 'protocols/' . $protocolID, [
                'debug' => false,
                'headers' => [
                    'Authorization' => "Bearer {$jwt}",
                ]
            ]);

            if ($response->getStatusCode() < 300) {
                $data = json_decode($response->getBody(), true);
                if (!empty($data)) {
                    $this->setOnCoreProtocol($data);
                    return $data;
                }
            }
        } catch (\Exception $e) {
            Entities::createException($e->getMessage());
            throw new \Exception($e->getMessage());
        }
    }

    public function searchOnCoreProtocolsViaIRB($irbNum)
    {
        try {
            $jwt = $this->getUser()->getAccessToken();
            $response = $this->getUser()->getGuzzleClient()->get($this->getUser()->getApiURL() . $this->getUser()->getApiURN() . 'protocolManagementDetails?irbNo=' . $irbNum, [
                'debug' => false,
                'headers' => [
                    'Authorization' => "Bearer {$jwt}",
                ]
            ]);

            if ($response->getStatusCode() < 300) {
                $data = json_decode($response->getBody(), true);
                if (empty($data)) {
                    return [];
                } else {
                    return $data;
                }
            }
        } catch (\Exception $e) {
            Entities::createException($e->getMessage());
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param $redcapProjectId
     * @param $irbNum
     * @return array|false|mixed|string[]|null
     * @throws \Exception
     */
    public function getProtocolEntityRecord($redcapProjectId, $irbNum = '')
    {
        if ($redcapProjectId == '') {
            throw new \Exception('REDCap project ID can not be null');
        }
        if ($irbNum != '') {
            $record = db_query("select * from " . OnCoreIntegration::REDCAP_ENTITY_ONCORE_PROTOCOLS . " where irb_number = " . $irbNum . " AND redcap_project_id = " . $redcapProjectId . " ");
        } else {
            $record = db_query("select * from " . OnCoreIntegration::REDCAP_ENTITY_ONCORE_PROTOCOLS . " where redcap_project_id = " . $redcapProjectId . " ");
        }
        if ($record->num_rows == 0) {
            return [];
        } else {
            return db_fetch_assoc($record);
        }
    }

    public function updateProtocolEntityRecordTimestamp($entityId)
    {
        db_query("UPDATE " . OnCoreIntegration::REDCAP_ENTITY_ONCORE_PROTOCOLS . " set last_date_scanned = '" . time() . "', updated = '" . time() . "' WHERE id = " . $entityId . "");
    }

    /**
     * @return Users
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param $user
     * @return void
     */
    public function setUser($user): void
    {
        $this->user = $user;
    }

    /**
     * @return array
     */
    public function getOnCoreProtocol(): array
    {
        return $this->onCoreProtocol;
    }

    /**
     * @param array $onCoreProtocol
     */
    public function setOnCoreProtocol(array $onCoreProtocol): void
    {
        $this->onCoreProtocol = $onCoreProtocol;
    }

    /**
     * @return array
     */
    public function getEntityRecord(): array
    {
        return $this->entityRecord;
    }

    /**
     * @param array $entityRecord
     */
    public function setEntityRecord(array $entityRecord): void
    {
        $this->entityRecord = $entityRecord;
    }

    /**
     * @return Subjects
     */
    public function getSubjects(): Subjects
    {
        return $this->subjects;
    }

    /**
     * @param Subjects $subjects
     */
    public function setSubjects(Subjects $subjects): void
    {
        $this->subjects = $subjects;
    }


}
