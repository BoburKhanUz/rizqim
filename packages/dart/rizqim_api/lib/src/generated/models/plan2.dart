// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'plan2.g.dart';

@JsonSerializable()
class Plan2 {
  const Plan2({required this.code, required this.name});

  factory Plan2.fromJson(Map<String, Object?> json) => _$Plan2FromJson(json);

  final String code;
  final String name;

  Map<String, Object?> toJson() => _$Plan2ToJson(this);
}
