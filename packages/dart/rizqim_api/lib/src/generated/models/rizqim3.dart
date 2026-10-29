// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rizqim3.g.dart';

@JsonSerializable()
class Rizqim3 {
  const Rizqim3({required this.rrn, required this.amount, required this.kind});

  factory Rizqim3.fromJson(Map<String, Object?> json) =>
      _$Rizqim3FromJson(json);

  final String? rrn;
  final String amount;
  final String kind;

  Map<String, Object?> toJson() => _$Rizqim3ToJson(this);
}
