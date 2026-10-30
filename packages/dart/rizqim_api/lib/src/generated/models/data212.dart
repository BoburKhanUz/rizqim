// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data212.g.dart';

@JsonSerializable()
class Data212 {
  const Data212({
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
    required this.createdAt,
    required this.updatedAt,
  });

  factory Data212.fromJson(Map<String, Object?> json) =>
      _$Data212FromJson(json);

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
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;

  Map<String, Object?> toJson() => _$Data212ToJson(this);
}
