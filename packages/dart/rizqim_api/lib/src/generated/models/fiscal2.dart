// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'fiscal2.g.dart';

@JsonSerializable()
class Fiscal2 {
  const Fiscal2({required this.status, required this.problems});

  factory Fiscal2.fromJson(Map<String, Object?> json) =>
      _$Fiscal2FromJson(json);

  final String status;
  final List<String> problems;

  Map<String, Object?> toJson() => _$Fiscal2ToJson(this);
}
