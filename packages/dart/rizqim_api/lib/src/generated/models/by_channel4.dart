// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_channel4.g.dart';

@JsonSerializable()
class ByChannel4 {
  const ByChannel4({
    required this.channel,
    required this.orders,
    required this.amount,
  });

  factory ByChannel4.fromJson(Map<String, Object?> json) =>
      _$ByChannel4FromJson(json);

  final String channel;
  final int orders;
  final String amount;

  Map<String, Object?> toJson() => _$ByChannel4ToJson(this);
}
