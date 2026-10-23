// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_channel3.g.dart';

@JsonSerializable()
class ByChannel3 {
  const ByChannel3({
    required this.channel,
    required this.orders,
    required this.amount,
  });

  factory ByChannel3.fromJson(Map<String, Object?> json) =>
      _$ByChannel3FromJson(json);

  final String channel;
  final int orders;
  final String amount;

  Map<String, Object?> toJson() => _$ByChannel3ToJson(this);
}
