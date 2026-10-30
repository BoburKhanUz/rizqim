// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'branch16.g.dart';

@JsonSerializable()
class Branch16 {
  const Branch16({required this.id, required this.name});

  factory Branch16.fromJson(Map<String, Object?> json) =>
      _$Branch16FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$Branch16ToJson(this);
}
