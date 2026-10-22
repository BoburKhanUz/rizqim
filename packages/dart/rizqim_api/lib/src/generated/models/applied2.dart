// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'applied2.g.dart';

@JsonSerializable()
class Applied2 {
  const Applied2({required this.count, required this.amount});

  factory Applied2.fromJson(Map<String, Object?> json) =>
      _$Applied2FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Applied2ToJson(this);
}
