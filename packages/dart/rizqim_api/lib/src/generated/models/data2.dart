// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data.dart';

part 'data2.g.dart';

@JsonSerializable()
class Data2 {
  const Data2({
    required this.data,
    required this.nextCursor,
    required this.prevCursor,
    required this.perPage,
  });

  factory Data2.fromJson(Map<String, Object?> json) => _$Data2FromJson(json);

  final List<Data> data;
  @JsonKey(name: 'next_cursor')
  final String? nextCursor;
  @JsonKey(name: 'prev_cursor')
  final String? prevCursor;
  @JsonKey(name: 'per_page')
  final int perPage;

  Map<String, Object?> toJson() => _$Data2ToJson(this);
}
