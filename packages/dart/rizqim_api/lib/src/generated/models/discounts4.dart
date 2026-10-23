// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'discounts4.g.dart';

@JsonSerializable()
class Discounts4 {
  const Discounts4({required this.count, required this.amount});

  factory Discounts4.fromJson(Map<String, Object?> json) =>
      _$Discounts4FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Discounts4ToJson(this);
}
