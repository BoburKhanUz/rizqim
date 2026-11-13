// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'blocked2.g.dart';

@JsonSerializable()
class Blocked2 {
  const Blocked2({required this.count, required this.amount});

  factory Blocked2.fromJson(Map<String, Object?> json) =>
      _$Blocked2FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Blocked2ToJson(this);
}
