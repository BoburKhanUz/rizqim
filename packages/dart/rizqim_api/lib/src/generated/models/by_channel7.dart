// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_channel7.g.dart';

@JsonSerializable()
class ByChannel7 {
  const ByChannel7({
    required this.channel,
    required this.orders,
    required this.amount,
  });

  factory ByChannel7.fromJson(Map<String, Object?> json) =>
      _$ByChannel7FromJson(json);

  final String channel;
  final int orders;
  final String amount;

  Map<String, Object?> toJson() => _$ByChannel7ToJson(this);
}
