// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'owner.dart';

part 'data178.g.dart';

@JsonSerializable()
class Data178 {
  const Data178({
    required this.id,
    required this.uuid,
    required this.name,
    required this.phone,
    required this.slug,
    required this.isActive,
    required this.commissionRate,
    required this.branchesCount,
    required this.createdAt,
    required this.owner,
  });

  factory Data178.fromJson(Map<String, Object?> json) =>
      _$Data178FromJson(json);

  final int id;
  final String uuid;
  final String name;
  final String? phone;
  final String slug;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'commission_rate')
  final String commissionRate;
  @JsonKey(name: 'branches_count')
  final int? branchesCount;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  final Owner? owner;

  Map<String, Object?> toJson() => _$Data178ToJson(this);
}
