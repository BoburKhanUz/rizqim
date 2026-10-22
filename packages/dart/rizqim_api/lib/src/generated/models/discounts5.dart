// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'discounts5.g.dart';

@JsonSerializable()
class Discounts5 {
  const Discounts5({required this.count, required this.amount});

  factory Discounts5.fromJson(Map<String, Object?> json) =>
      _$Discounts5FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Discounts5ToJson(this);
}
