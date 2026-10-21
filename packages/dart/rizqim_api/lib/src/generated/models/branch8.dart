// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'branch8.g.dart';

@JsonSerializable()
class Branch8 {
  const Branch8({required this.id, required this.name});

  factory Branch8.fromJson(Map<String, Object?> json) =>
      _$Branch8FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$Branch8ToJson(this);
}
