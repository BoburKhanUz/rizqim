// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_channel2.g.dart';

@JsonSerializable()
class ByChannel2 {
  const ByChannel2({
    required this.channel,
    required this.label,
    required this.orders,
    required this.revenue,
  });

  factory ByChannel2.fromJson(Map<String, Object?> json) =>
      _$ByChannel2FromJson(json);

  final String channel;
  final String label;
  final int orders;
  final String revenue;

  Map<String, Object?> toJson() => _$ByChannel2ToJson(this);
}
