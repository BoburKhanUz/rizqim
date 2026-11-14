// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'lan_role.dart';
import 'hub5.dart';
import 'devices4.dart';

part 'data294.g.dart';

@JsonSerializable()
class Data294 {
  const Data294({
    required this.v,
    required this.branchId,
    required this.keyId,
    required this.role,
    required this.device,
    required this.deviceKey,
    required this.beaconKey,
    required this.hub,
    required this.hubSecret,
    required this.devices,
    required this.grant,
    required this.clockWindowSeconds,
  });

  factory Data294.fromJson(Map<String, Object?> json) =>
      _$Data294FromJson(json);

  final int v;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'key_id')
  final int keyId;
  final LanRole role;
  final String device;
  @JsonKey(name: 'device_key')
  final String deviceKey;
  @JsonKey(name: 'beacon_key')
  final String beaconKey;
  final Hub5 hub;
  @JsonKey(name: 'hub_secret')
  final String? hubSecret;
  final List<Devices4>? devices;
  final String? grant;
  @JsonKey(name: 'clock_window_seconds')
  final int clockWindowSeconds;

  Map<String, Object?> toJson() => _$Data294ToJson(this);
}
