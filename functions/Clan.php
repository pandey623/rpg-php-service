<?php
function CreateClan($clanName)
{
    $gameData = \Base::instance()->get('GameData');
    $output = array('error' => '');
    $player = GetPlayer();
    $playerId = $player->id;
    $clanId = $player->clanId;

    if (!empty($clanId)) {
        $output['error'] = 'ERROR_JOINED_CLAN';
    } else {
        $softCurrency = GetCurrency($playerId, $gameData['currencies']['SOFT_CURRENCY']['id']);
        $hardCurrency = GetCurrency($playerId, $gameData['currencies']['HARD_CURRENCY']['id']);
        $requirementType = $gameData['createClanCurrencyType'];
        $price = $gameData['createClanCurrencyAmount'];
        if ($requirementType == ECreateClanRequirementType::SoftCurrency && $price > $softCurrency->amount) {
            $output['error'] = 'ERROR_NOT_ENOUGH_SOFT_CURRENCY';
        } else if ($requirementType == ECreateClanRequirementType::HardCurrency && $price > $hardCurrency->amount) {
            $output['error'] = 'ERROR_NOT_ENOUGH_HARD_CURRENCY';
        } else {
            switch ($requirementType)
            {
                case ECreateClanRequirementType::SoftCurrency:
                    $softCurrency->amount -= $price;
                    $softCurrency->update();
                    $updateCurrencies[] = $softCurrency;
                    break;
                case ECreateClanRequirementType::HardCurrency:
                    $hardCurrency->amount -= $price;
                    $hardCurrency->update();
                    $updateCurrencies[] = $hardCurrency;
                    break;
            }
            $clan = new Clan();
            $clan->ownerId = $playerId;
            $clan->name = $clanName;
            $clan->save();
            $player->clanId = $clan->id;
            $player->update();

            $output['clan'] = array(
                'id' => $clan->id,
                'ownerId' => $clan->ownerId,
                'name' => $clan->name,
                'owner' => GetSocialPlayer($playerId, $clan->ownerId)
            );
            $output['updateCurrencies'] = CursorsToArray($updateCurrencies);
        }
    }
    echo json_encode($output);
}

function FindClan($clanName)
{
    $player = GetPlayer();
    $playerId = $player->id;
    $list = array();
    $clanDb = new Clan();
    if (empty($clanName)) {
        $foundClans = $clanDb->find(array(
        ), array('limit' => 25));
    }
    else
    {
        $foundClans = $clanDb->find(array(
            'name = ?',
            $clanName.'%'
        ), array('limit' => 25));
    }
    // Add list
    foreach ($foundClans as $foundClan) {
        $list[] = array(
            'id' => $foundClan->id,
            'ownerId' => $foundClan->ownerId,
            'name' => $foundClan->name,
            'owner' => GetSocialPlayer($playerId, $foundClan->ownerId)
        );
    }
    echo json_encode(array('list' => $list));
}

function ClanJoinRequest($clanId)
{
    $output = array('error' => '');
    $player = GetPlayer();
    $playerId = $player->id;
    $clanId = $player->clanId;

    if (!empty($clanId)) {
        $output['error'] = 'ERROR_JOINED_CLAN';
    } else {
        // Delete request to this clan (if found)
        $clanJoinRequest = new ClanJoinRequest();
        $clanJoinRequest->erase(array('playerId = ? AND clanId = ?'), $playerId, $clanId);
        // Create new request record
        $clanJoinRequest = new ClanJoinRequest();
        $clanJoinRequest->playerId = $playerId;
        $clanJoinRequest->clanId = $clanId;
        $clanJoinRequest->save();
    }
    echo json_encode($output);
}

function ClanJoinAccept($targetPlayerId)
{
    $output = array('error' => '');
    $player = GetPlayer();
    $playerId = $player->id;
    $clanId = $player->clanId;

    $clanDb = new Clan();
    $countClan = $clanDb->count(array('ownerId = ?', $playerId));
    if ($countClan <= 0) {
        $output['error'] = 'ERROR_NOT_CLAN_OWNER';
    } else {
        $clanJoinRequest = new ClanJoinRequest();
        $countRequest = $clanJoinRequest->count(array('playerId = ? AND clanId = ?'), $targetPlayerId, $clanId);
        if ($countRequest > 0) {
            // Delete request record
            $clanJoinRequest = new ClanJoinRequest();
            $clanJoinRequest->erase(array('playerId = ?'), $targetPlayerId);
            // Update clan ID
            $memberDb = new Player();
            $member = $memberDb->load(array(
                'id = ?',
                $targetPlayerId,
            ));
            if (empty($member->clanId)) {
                $member->clanId = $clanId;
                $member->update();
            }
        }
    }
    echo json_encode($output);
}

function ClanJoinDecline($targetPlayerId)
{
    $output = array('error' => '');
    $player = GetPlayer();
    $playerId = $player->id;
    $clanId = $player->clanId;

    $clanDb = new Clan();
    $countClan = $clanDb->count(array('ownerId = ?', $playerId));
    if ($countClan <= 0) {
        $output['error'] = 'ERROR_NOT_CLAN_OWNER';
    } else {
        $clanJoinRequest = new ClanJoinRequest();
        $countRequest = $clanJoinRequest->count(array('playerId = ? AND clanId = ?'), $targetPlayerId);
        if ($countRequest > 0) {
            // Delete request record
            $clanJoinRequest = new ClanJoinRequest();
            $clanJoinRequest->erase(array('playerId = ? AND clanId = ?'), $targetPlayerId, $clanId);
        }
    }
    echo json_encode($output);
}

function ClanMemberDelete($targetPlayerId)
{
    $output = array('error' => '');
    $player = GetPlayer();
    $playerId = $player->id;
    $clanId = $player->clanId;

    $clanDb = new Clan();
    $clan = $clanDb->load(array('ownerId = ?', $playerId));
    if (!$clan) {
        $output['error'] = 'ERROR_NOT_CLAN_OWNER';
    } else if ($clan->ownerId == $targetPlayerId) {
        $output['error'] = 'ERROR_CANNOT_DELETE_CLAN_OWNER';
    } else {
        $memberDb = new Player();
        $member = $memberDb->load(array('id = ?', $targetPlayerId));
        if ($member->clanId == $clanId) {
            $member->clanId = 0;
            $member->update();
        } else {
            $output['error'] = 'ERROR_NOT_CLAN_MEMBER';
        }
    }
    echo json_encode($output);
}

function ClanJoinRequestDelete($clanId)
{
    $output = array('error' => '');
    $player = GetPlayer();
    $playerId = $player->id;
    // Delete request record
    $clanJoinRequest = new ClanJoinRequest();
    $clanJoinRequest->erase(array('playerId = ? AND clanId = ?'), $playerId, $clanId);
    echo json_encode($output);
}

function ClanMembers()
{
    $player = GetPlayer();
    $playerId = $player->id;
    $clanId = $player->clanId;
    $list = array();
    if (!empty($clanId)) {
        $playerDb = new Player();
        $foundPlayers = $playerDb->find(array('clanId = ?', $clanId));
        // Add list
        foreach ($foundPlayers as $foundPlayer) {
            $socialPlayer = GetSocialPlayer($playerId, $foundPlayer->id);
            if ($socialPlayer) {
                $list[] = $socialPlayer;
            }
        }
    }
    echo json_encode(array('list' => $list));
}

function ClanOwnerTransfer($targetPlayerId)
{
    $output = array('error' => '');
    $player = GetPlayer();
    $playerId = $player->id;
    $clanId = $player->clanId;
    $clanDb = new Clan();
    $countClan = $clanDb->count(array('ownerId = ?', $playerId));
    if ($countClan <= 0) {
        $output['error'] = 'ERROR_NOT_CLAN_OWNER';
    } else {
        $memberDb = new Player();
        $member = $memberDb->find(array('id = ?', $targetPlayerId));
        if ($member && $member->clanId == $clanId)
        {
            $clanDb = new Clan();
            $clanDb->ownerId = $member->id;
            $clanDb->update();
        }
    }
    echo json_encode($output);
}

function ClanTerminate()
{
    $output = array('error' => '');
    $player = GetPlayer();
    $playerId = $player->id;
    $clanId = $player->clanId;
    $clanDb = new Clan();
    $countClan = $clanDb->count(array('ownerId = ?', $playerId));
    if ($countClan <= 0) {
        $output['error'] = 'ERROR_NOT_CLAN_OWNER';
    } else {
        $clanDb = new Clan();
        $clanDb->erase(array('ownerId = ?', $playerId));
        $db = \Base::instance()->get('DB');
        $prefix = \Base::instance()->get('db_prefix');
        $db->exec('UPDATE ' . $prefix . 'player SET clanId=0 WHERE clanId="' . $clanId . '"');
    }
    echo json_encode($output);
}

function GetClan()
{
    $output = array('error' => '');
    $player = GetPlayer();
    $playerId = $player->id;
    $clanId = $player->clanId;

    $clanDb = new Clan();
    $clan = $clanDb->load(array('id = ?', $clanId));
    if ($clan) {
        $output['clan'] = array(
            'id' => $clan->id,
            'ownerId' => $clan->ownerId,
            'name' => $clan->name,
            'owner' => GetSocialPlayer($playerId, $clan->ownerId)
        );
    }
    echo json_encode($output);
}
?>