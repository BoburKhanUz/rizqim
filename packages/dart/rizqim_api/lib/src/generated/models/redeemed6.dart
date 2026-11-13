// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'redeemed6.g.dart';

@JsonSerializable()
class Redeemed6 {
  const Redeemed6({required this.count, required this.amount});

  factory Redeemed6.fromJson(Map<String, Object?> json) =>
      _$Redeemed6FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Redeemed6ToJson(this);
}
