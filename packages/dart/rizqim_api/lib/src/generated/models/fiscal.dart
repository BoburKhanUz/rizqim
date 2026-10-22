// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'fiscal.g.dart';

@JsonSerializable()
class Fiscal {
  const Fiscal({required this.status, required this.problems});

  factory Fiscal.fromJson(Map<String, Object?> json) => _$FiscalFromJson(json);

  final String status;
  final List<String> problems;

  Map<String, Object?> toJson() => _$FiscalToJson(this);
}
