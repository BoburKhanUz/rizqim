// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'redeemed3.g.dart';

@JsonSerializable()
class Redeemed3 {
  const Redeemed3({required this.count, required this.amount});

  factory Redeemed3.fromJson(Map<String, Object?> json) =>
      _$Redeemed3FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Redeemed3ToJson(this);
}
