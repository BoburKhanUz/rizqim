// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'blocks.g.dart';

@JsonSerializable()
class Blocks {
  const Blocks({required this.start, required this.end, required this.next});

  factory Blocks.fromJson(Map<String, Object?> json) => _$BlocksFromJson(json);

  final int start;
  final int end;
  final int next;

  Map<String, Object?> toJson() => _$BlocksToJson(this);
}
