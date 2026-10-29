// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'kassa_status2.dart';

part 'devices2.g.dart';

@JsonSerializable()
class Devices2 {
  const Devices2({
    required this.uuid,
    required this.name,
    required this.branchId,
    required this.smartKassa,
    required this.fiscalKassaId,
    required this.kassaStatus,
    required this.lastSeenAt,
  });

  factory Devices2.fromJson(Map<String, Object?> json) =>
      _$Devices2FromJson(json);

  final String uuid;
  final String? name;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'smart_kassa')
  final bool smartKassa;
  @JsonKey(name: 'fiscal_kassa_id')
  final String? fiscalKassaId;
  @JsonKey(name: 'kassa_status')
  final KassaStatus2? kassaStatus;
  @JsonKey(name: 'last_seen_at')
  final String? lastSeenAt;

  Map<String, Object?> toJson() => _$Devices2ToJson(this);
}
