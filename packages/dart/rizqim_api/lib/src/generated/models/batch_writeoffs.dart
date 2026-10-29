// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'items42.dart';

part 'batch_writeoffs.g.dart';

@JsonSerializable()
class BatchWriteoffs {
  const BatchWriteoffs({required this.count, required this.items});

  factory BatchWriteoffs.fromJson(Map<String, Object?> json) =>
      _$BatchWriteoffsFromJson(json);

  final int count;
  final List<Items42> items;

  Map<String, Object?> toJson() => _$BatchWriteoffsToJson(this);
}
