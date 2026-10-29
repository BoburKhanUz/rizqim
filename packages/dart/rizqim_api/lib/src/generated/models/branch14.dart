// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'branch14.g.dart';

@JsonSerializable()
class Branch14 {
  const Branch14({required this.id, required this.name});

  factory Branch14.fromJson(Map<String, Object?> json) =>
      _$Branch14FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$Branch14ToJson(this);
}
