// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'done_by2.g.dart';

@JsonSerializable()
class DoneBy2 {
  const DoneBy2({required this.id, required this.name});

  factory DoneBy2.fromJson(Map<String, Object?> json) =>
      _$DoneBy2FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$DoneBy2ToJson(this);
}
