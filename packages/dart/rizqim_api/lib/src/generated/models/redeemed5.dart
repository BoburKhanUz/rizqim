// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'redeemed5.g.dart';

@JsonSerializable()
class Redeemed5 {
  const Redeemed5({required this.count, required this.amount});

  factory Redeemed5.fromJson(Map<String, Object?> json) =>
      _$Redeemed5FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Redeemed5ToJson(this);
}
