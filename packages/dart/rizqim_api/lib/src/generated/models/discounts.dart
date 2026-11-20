// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'offline27.dart';

part 'discounts.g.dart';

@JsonSerializable()
class Discounts {
  const Discounts({required this.count, required this.amount, this.offline});

  factory Discounts.fromJson(Map<String, Object?> json) =>
      _$DiscountsFromJson(json);

  final int count;
  final String amount;
  final Offline27? offline;

  Map<String, Object?> toJson() => _$DiscountsToJson(this);
}
