// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'discounts2.g.dart';

@JsonSerializable()
class Discounts2 {
  const Discounts2({required this.count, required this.amount});

  factory Discounts2.fromJson(Map<String, Object?> json) =>
      _$Discounts2FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Discounts2ToJson(this);
}
