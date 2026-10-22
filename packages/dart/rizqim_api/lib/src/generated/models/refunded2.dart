// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'refunded2.g.dart';

@JsonSerializable()
class Refunded2 {
  const Refunded2({
    required this.count,
    required this.amount,
    required this.cash,
  });

  factory Refunded2.fromJson(Map<String, Object?> json) =>
      _$Refunded2FromJson(json);

  final int count;
  final String amount;
  final String cash;

  Map<String, Object?> toJson() => _$Refunded2ToJson(this);
}
