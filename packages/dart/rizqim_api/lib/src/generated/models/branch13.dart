// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'branch13.g.dart';

@JsonSerializable()
class Branch13 {
  const Branch13({required this.id, required this.name});

  factory Branch13.fromJson(Map<String, Object?> json) =>
      _$Branch13FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$Branch13ToJson(this);
}
