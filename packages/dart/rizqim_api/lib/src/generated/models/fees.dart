// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'fees.g.dart';

@JsonSerializable()
class Fees {
  const Fees({
    required this.hallId,
    required this.channel,
    required this.serviceChargePercent,
    required this.placeFeeAmount,
    required this.placeFeeMode,
  });

  factory Fees.fromJson(Map<String, Object?> json) => _$FeesFromJson(json);

  @JsonKey(name: 'hall_id')
  final int? hallId;
  final String channel;
  @JsonKey(name: 'service_charge_percent')
  final String serviceChargePercent;
  @JsonKey(name: 'place_fee_amount')
  final String placeFeeAmount;
  @JsonKey(name: 'place_fee_mode')
  final String placeFeeMode;

  Map<String, Object?> toJson() => _$FeesToJson(this);
}
