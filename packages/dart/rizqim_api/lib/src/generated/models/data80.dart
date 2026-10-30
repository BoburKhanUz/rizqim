// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'user_role.dart';
import 'courier_type.dart';
import 'branch7.dart';

part 'data80.g.dart';

@JsonSerializable()
class Data80 {
  const Data80({
    required this.id,
    required this.uuid,
    required this.storeId,
    required this.name,
    required this.email,
    required this.telegramId,
    required this.telegramUsername,
    required this.telegramPhotoUrl,
    required this.phone,
    required this.role,
    required this.emailVerifiedAt,
    required this.createdAt,
    required this.updatedAt,
    required this.deletedAt,
    required this.address,
    required this.phoneVerifiedAt,
    required this.branchId,
    required this.referredBy,
    required this.balance,
    required this.pinSetAt,
    required this.staffCode,
    required this.courierType,
    required this.branch,
    required this.telegramInviteToken,
  });

  factory Data80.fromJson(Map<String, Object?> json) => _$Data80FromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'store_id')
  final int? storeId;
  final String name;
  final String? email;
  @JsonKey(name: 'telegram_id')
  final String? telegramId;
  @JsonKey(name: 'telegram_username')
  final String? telegramUsername;
  @JsonKey(name: 'telegram_photo_url')
  final String? telegramPhotoUrl;
  final String? phone;
  final UserRole role;
  @JsonKey(name: 'email_verified_at')
  final String? emailVerifiedAt;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;
  @JsonKey(name: 'deleted_at')
  final String? deletedAt;
  final String? address;
  @JsonKey(name: 'phone_verified_at')
  final String? phoneVerifiedAt;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'referred_by')
  final int? referredBy;
  final String balance;
  @JsonKey(name: 'pin_set_at')
  final String? pinSetAt;
  @JsonKey(name: 'staff_code')
  final String? staffCode;
  @JsonKey(name: 'courier_type')
  final CourierType courierType;
  final Branch7? branch;
  @JsonKey(name: 'telegram_invite_token')
  final String? telegramInviteToken;

  Map<String, Object?> toJson() => _$Data80ToJson(this);
}
