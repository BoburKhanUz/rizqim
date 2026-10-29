// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rizqim5.g.dart';

@JsonSerializable()
class Rizqim5 {
  const Rizqim5({required this.rrn, required this.amount, required this.kind});

  factory Rizqim5.fromJson(Map<String, Object?> json) =>
      _$Rizqim5FromJson(json);

  final String? rrn;
  final String amount;
  final String kind;

  Map<String, Object?> toJson() => _$Rizqim5ToJson(this);
}
