// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'customer_account_kind.dart';
import 'talon_mode.dart';

part 'account6.g.dart';

@JsonSerializable()
class Account6 {
  const Account6({
    required this.uuid,
    required this.name,
    required this.phone,
    required this.groupName,
    required this.branchId,
    required this.creditLimit,
    required this.balance,
    required this.availableCredit,
    required this.isBlocked,
    required this.note,
    required this.kind,
    required this.kindLabel,
    required this.companyName,
    required this.inn,
    required this.contactName,
    required this.contactPhone,
    required this.invoiceEmail,
    required this.monthlyLimit,
    required this.chargeApproval,
    required this.talonMode,
    required this.membersCount,
    required this.createdAt,
    required this.updatedAt,
  });

  factory Account6.fromJson(Map<String, Object?> json) =>
      _$Account6FromJson(json);

  final String uuid;
  final String name;
  final String? phone;
  @JsonKey(name: 'group_name')
  final String? groupName;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'credit_limit')
  final String? creditLimit;
  final String balance;
  @JsonKey(name: 'available_credit')
  final String? availableCredit;
  @JsonKey(name: 'is_blocked')
  final bool isBlocked;
  final String? note;
  final CustomerAccountKind kind;
  @JsonKey(name: 'kind_label')
  final String kindLabel;
  @JsonKey(name: 'company_name')
  final String? companyName;
  final String? inn;
  @JsonKey(name: 'contact_name')
  final String? contactName;
  @JsonKey(name: 'contact_phone')
  final String? contactPhone;
  @JsonKey(name: 'invoice_email')
  final String? invoiceEmail;
  @JsonKey(name: 'monthly_limit')
  final String? monthlyLimit;
  @JsonKey(name: 'charge_approval')
  final bool chargeApproval;
  @JsonKey(name: 'talon_mode')
  final TalonMode talonMode;
  @JsonKey(name: 'members_count')
  final int membersCount;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;

  Map<String, Object?> toJson() => _$Account6ToJson(this);
}
