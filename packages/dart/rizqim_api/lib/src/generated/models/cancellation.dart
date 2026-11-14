// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'cancellation.g.dart';

@JsonSerializable()
class Cancellation {
  const Cancellation({
    required this.daysBefore,
    required this.rulePercent,
    required this.paid,
    required this.computedRetained,
    required this.retained,
    required this.refund,
    required this.overrideReason,
    required this.reason,
    required this.businessDate,
    required this.cancelledBy,
    required this.createdAt,
  });

  factory Cancellation.fromJson(Map<String, Object?> json) =>
      _$CancellationFromJson(json);

  @JsonKey(name: 'days_before')
  final int daysBefore;
  @JsonKey(name: 'rule_percent')
  final String rulePercent;
  final String paid;
  @JsonKey(name: 'computed_retained')
  final String computedRetained;
  final String retained;
  final String refund;
  @JsonKey(name: 'override_reason')
  final String? overrideReason;
  final String? reason;
  @JsonKey(name: 'business_date')
  final String? businessDate;
  @JsonKey(name: 'cancelled_by')
  final int? cancelledBy;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$CancellationToJson(this);
}
