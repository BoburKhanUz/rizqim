// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'corrections11.g.dart';

@JsonSerializable()
class Corrections11 {
  const Corrections11({required this.count, required this.amount});

  factory Corrections11.fromJson(Map<String, Object?> json) =>
      _$Corrections11FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Corrections11ToJson(this);
}
