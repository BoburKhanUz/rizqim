// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'redeemed.g.dart';

@JsonSerializable()
class Redeemed {
  const Redeemed({required this.count, required this.amount});

  factory Redeemed.fromJson(Map<String, Object?> json) =>
      _$RedeemedFromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$RedeemedToJson(this);
}
