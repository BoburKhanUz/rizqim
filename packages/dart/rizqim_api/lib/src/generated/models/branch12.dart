// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'branch12.g.dart';

@JsonSerializable()
class Branch12 {
  const Branch12({required this.id, required this.name});

  factory Branch12.fromJson(Map<String, Object?> json) =>
      _$Branch12FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$Branch12ToJson(this);
}
