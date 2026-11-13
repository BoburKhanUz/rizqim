// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'redeemed4.g.dart';

@JsonSerializable()
class Redeemed4 {
  const Redeemed4({required this.count, required this.amount});

  factory Redeemed4.fromJson(Map<String, Object?> json) =>
      _$Redeemed4FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Redeemed4ToJson(this);
}
