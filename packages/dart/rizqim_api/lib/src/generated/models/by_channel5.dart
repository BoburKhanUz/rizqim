// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_channel5.g.dart';

@JsonSerializable()
class ByChannel5 {
  const ByChannel5({
    required this.channel,
    required this.orders,
    required this.amount,
  });

  factory ByChannel5.fromJson(Map<String, Object?> json) =>
      _$ByChannel5FromJson(json);

  final String channel;
  final int orders;
  final String amount;

  Map<String, Object?> toJson() => _$ByChannel5ToJson(this);
}
