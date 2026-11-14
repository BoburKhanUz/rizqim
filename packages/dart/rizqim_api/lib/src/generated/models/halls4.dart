// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'halls4.g.dart';

@JsonSerializable()
class Halls4 {
  const Halls4({required this.id, required this.name});

  factory Halls4.fromJson(Map<String, Object?> json) => _$Halls4FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$Halls4ToJson(this);
}
