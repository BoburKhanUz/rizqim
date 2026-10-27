// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'user.dart';

part 'device.g.dart';

@JsonSerializable()
class Device {
  const Device({
    required this.uuid,
    required this.type,
    required this.name,
    required this.platform,
    required this.appVersion,
    required this.storeId,
    required this.branchId,
    required this.user,
    required this.hasPushToken,
    required this.lastSeenAt,
    required this.revokedAt,
    required this.createdAt,
  });

  factory Device.fromJson(Map<String, Object?> json) => _$DeviceFromJson(json);

  final String uuid;
  final String type;
  final String name;
  final String? platform;
  @JsonKey(name: 'app_version')
  final String? appVersion;
  @JsonKey(name: 'store_id')
  final int? storeId;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  final User? user;
  @JsonKey(name: 'has_push_token')
  final bool hasPushToken;
  @JsonKey(name: 'last_seen_at')
  final String? lastSeenAt;
  @JsonKey(name: 'revoked_at')
  final String? revokedAt;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$DeviceToJson(this);
}
