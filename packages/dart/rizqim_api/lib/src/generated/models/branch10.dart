// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'branch10.g.dart';

@JsonSerializable()
class Branch10 {
  const Branch10({required this.id, required this.name});

  factory Branch10.fromJson(Map<String, Object?> json) =>
      _$Branch10FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$Branch10ToJson(this);
}
