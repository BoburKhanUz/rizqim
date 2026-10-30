// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'items51.dart';

part 'batch_writeoffs5.g.dart';

@JsonSerializable()
class BatchWriteoffs5 {
  const BatchWriteoffs5({required this.count, required this.items});

  factory BatchWriteoffs5.fromJson(Map<String, Object?> json) =>
      _$BatchWriteoffs5FromJson(json);

  final int count;
  final List<Items51> items;

  Map<String, Object?> toJson() => _$BatchWriteoffs5ToJson(this);
}
