// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'voids2.g.dart';

@JsonSerializable()
class Voids2 {
  const Voids2({required this.count, required this.amount});

  factory Voids2.fromJson(Map<String, Object?> json) => _$Voids2FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Voids2ToJson(this);
}
