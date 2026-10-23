// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'voids4.g.dart';

@JsonSerializable()
class Voids4 {
  const Voids4({required this.count, required this.amount});

  factory Voids4.fromJson(Map<String, Object?> json) => _$Voids4FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Voids4ToJson(this);
}
