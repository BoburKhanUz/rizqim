// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch9.dart';

part 'data73.g.dart';

@JsonSerializable()
class Data73 {
  const Data73({
    required this.id,
    required this.name,
    required this.role,
    required this.roleLabel,
    required this.branch,
    required this.courierType,
    required this.phone,
    required this.loginPhoneEnabled,
    required this.hasPin,
    required this.staffCode,
    required this.telegramLinked,
    required this.invitePending,
    required this.canEdit,
    required this.createdAt,
  });

  factory Data73.fromJson(Map<String, Object?> json) => _$Data73FromJson(json);

  final int id;
  final String name;
  final String role;
  @JsonKey(name: 'role_label')
  final String roleLabel;
  final Branch9? branch;
  @JsonKey(name: 'courier_type')
  final String? courierType;
  final String? phone;
  @JsonKey(name: 'login_phone_enabled')
  final bool loginPhoneEnabled;
  @JsonKey(name: 'has_pin')
  final bool hasPin;
  @JsonKey(name: 'staff_code')
  final String? staffCode;
  @JsonKey(name: 'telegram_linked')
  final bool telegramLinked;
  @JsonKey(name: 'invite_pending')
  final bool invitePending;
  @JsonKey(name: 'can_edit')
  final bool canEdit;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Data73ToJson(this);
}
