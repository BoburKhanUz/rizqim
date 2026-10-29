// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rizqim2.g.dart';

@JsonSerializable()
class Rizqim2 {
  const Rizqim2({required this.rrn, required this.amount, required this.kind});

  factory Rizqim2.fromJson(Map<String, Object?> json) =>
      _$Rizqim2FromJson(json);

  final String? rrn;
  final String amount;
  final String kind;

  Map<String, Object?> toJson() => _$Rizqim2ToJson(this);
}
