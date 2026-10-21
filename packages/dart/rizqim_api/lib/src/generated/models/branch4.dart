// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'branch4.g.dart';

@JsonSerializable()
class Branch4 {
  const Branch4({required this.id, required this.name});

  factory Branch4.fromJson(Map<String, Object?> json) =>
      _$Branch4FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$Branch4ToJson(this);
}
