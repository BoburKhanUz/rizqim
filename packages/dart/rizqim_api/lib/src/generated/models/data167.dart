// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data167.g.dart';

@JsonSerializable()
class Data167 {
  const Data167({
    required this.period,
    required this.periodLabel,
    required this.salesAttribution,
    required this.salesAttributionLabel,
  });

  factory Data167.fromJson(Map<String, Object?> json) =>
      _$Data167FromJson(json);

  final String period;
  @JsonKey(name: 'period_label')
  final String periodLabel;
  @JsonKey(name: 'sales_attribution')
  final String salesAttribution;
  @JsonKey(name: 'sales_attribution_label')
  final String salesAttributionLabel;

  Map<String, Object?> toJson() => _$Data167ToJson(this);
}
