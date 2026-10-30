// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hall19.g.dart';

@JsonSerializable()
class Hall19 {
  const Hall19({required this.id, required this.name});

  factory Hall19.fromJson(Map<String, Object?> json) => _$Hall19FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Hall19ToJson(this);
}
