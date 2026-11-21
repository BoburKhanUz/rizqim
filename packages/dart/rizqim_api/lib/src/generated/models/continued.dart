// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'continued.g.dart';

@JsonSerializable()
class Continued {
  const Continued({required this.count, required this.amount});

  factory Continued.fromJson(Map<String, Object?> json) =>
      _$ContinuedFromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$ContinuedToJson(this);
}
