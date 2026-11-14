// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data294.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data294 _$Data294FromJson(Map<String, dynamic> json) => Data294(
  v: (json['v'] as num).toInt(),
  branchId: (json['branch_id'] as num).toInt(),
  keyId: (json['key_id'] as num).toInt(),
  role: LanRole.fromJson(json['role'] as String),
  device: json['device'] as String,
  deviceKey: json['device_key'] as String,
  beaconKey: json['beacon_key'] as String,
  hub: Hub5.fromJson(json['hub'] as Map<String, dynamic>),
  hubSecret: json['hub_secret'] as String?,
  devices: (json['devices'] as List<dynamic>?)
      ?.map((e) => Devices4.fromJson(e as Map<String, dynamic>))
      .toList(),
  grant: json['grant'] as String?,
  clockWindowSeconds: (json['clock_window_seconds'] as num).toInt(),
);

Map<String, dynamic> _$Data294ToJson(Data294 instance) => <String, dynamic>{
  'v': instance.v,
  'branch_id': instance.branchId,
  'key_id': instance.keyId,
  'role': instance.role,
  'device': instance.device,
  'device_key': instance.deviceKey,
  'beacon_key': instance.beaconKey,
  'hub': instance.hub,
  'hub_secret': instance.hubSecret,
  'devices': instance.devices,
  'grant': instance.grant,
  'clock_window_seconds': instance.clockWindowSeconds,
};
