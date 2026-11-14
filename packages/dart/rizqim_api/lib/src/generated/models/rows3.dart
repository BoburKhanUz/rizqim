// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'rules2.dart';

part 'rows3.g.dart';

@JsonSerializable()
class Rows3 {
  const Rows3({
    required this.branchId,
    required this.hallId,
    required this.hallName,
    required this.serviceChargePercent,
    required this.serviceChargeChannels,
    required this.placeFeeAmount,
    required this.placeFeeMode,
    required this.rules,
    required this.notice,
    required this.noticePreview,
  });

  factory Rows3.fromJson(Map<String, Object?> json) => _$Rows3FromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'hall_id')
  final int? hallId;
  @JsonKey(name: 'hall_name')
  final String? hallName;
  @JsonKey(name: 'service_charge_percent')
  final String serviceChargePercent;
  @JsonKey(name: 'service_charge_channels')
  final List<String>? serviceChargeChannels;
  @JsonKey(name: 'place_fee_amount')
  final String placeFeeAmount;
  @JsonKey(name: 'place_fee_mode')
  final String placeFeeMode;
  final Rules2 rules;
  final String? notice;
  @JsonKey(name: 'notice_preview')
  final String? noticePreview;

  Map<String, Object?> toJson() => _$Rows3ToJson(this);
}
