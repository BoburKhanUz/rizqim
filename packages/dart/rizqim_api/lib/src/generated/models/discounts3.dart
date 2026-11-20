// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'offline29.dart';

part 'discounts3.g.dart';

@JsonSerializable()
class Discounts3 {
  const Discounts3({required this.count, required this.amount, this.offline});

  factory Discounts3.fromJson(Map<String, Object?> json) =>
      _$Discounts3FromJson(json);

  final int count;
  final String amount;
  final Offline29? offline;

  Map<String, Object?> toJson() => _$Discounts3ToJson(this);
}
