// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch12.dart';
import 'login_phone_proof.dart';

part 'data175.g.dart';

@JsonSerializable()
class Data175 {
  const Data175({
    required this.id,
    required this.name,
    required this.role,
    required this.roleLabel,
    required this.branch,
    required this.courierType,
    required this.phone,
    required this.loginPhoneEnabled,
    required this.loginPhoneProof,
    required this.hasPin,
    required this.staffCode,
    required this.telegramLinked,
    required this.invitePending,
    required this.canEdit,
    required this.createdAt,
  });

  factory Data175.fromJson(Map<String, Object?> json) =>
      _$Data175FromJson(json);

  final int id;
  final String name;
  final String role;
  @JsonKey(name: 'role_label')
  final String roleLabel;
  final Branch12? branch;
  @JsonKey(name: 'courier_type')
  final String? courierType;
  final String? phone;
  @JsonKey(name: 'login_phone_enabled')
  final bool loginPhoneEnabled;
  @JsonKey(name: 'login_phone_proof')
  final LoginPhoneProof? loginPhoneProof;
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

  Map<String, Object?> toJson() => _$Data175ToJson(this);
}
