// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'kassa_status.dart';

part 'data90.g.dart';

@JsonSerializable()
class Data90 {
  const Data90({
    required this.uuid,
    required this.name,
    required this.branchId,
    required this.smartKassa,
    required this.fiscalKassaId,
    required this.kassaStatus,
    required this.lastSeenAt,
  });

  factory Data90.fromJson(Map<String, Object?> json) => _$Data90FromJson(json);

  final String uuid;
  final String? name;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'smart_kassa')
  final bool smartKassa;
  @JsonKey(name: 'fiscal_kassa_id')
  final String? fiscalKassaId;
  @JsonKey(name: 'kassa_status')
  final KassaStatus? kassaStatus;
  @JsonKey(name: 'last_seen_at')
  final String? lastSeenAt;

  Map<String, Object?> toJson() => _$Data90ToJson(this);
}
