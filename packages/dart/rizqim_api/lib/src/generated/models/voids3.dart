// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'voids3.g.dart';

@JsonSerializable()
class Voids3 {
  const Voids3({required this.count, required this.amount});

  factory Voids3.fromJson(Map<String, Object?> json) => _$Voids3FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Voids3ToJson(this);
}
