// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'redeemed2.g.dart';

@JsonSerializable()
class Redeemed2 {
  const Redeemed2({required this.count, required this.amount});

  factory Redeemed2.fromJson(Map<String, Object?> json) =>
      _$Redeemed2FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Redeemed2ToJson(this);
}
