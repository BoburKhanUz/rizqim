// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hall9.g.dart';

@JsonSerializable()
class Hall9 {
  const Hall9({required this.id, required this.name});

  factory Hall9.fromJson(Map<String, Object?> json) => _$Hall9FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Hall9ToJson(this);
}
