// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'entries13.dart';
import 'summary14.dart';

part 'data355.g.dart';

@JsonSerializable()
class Data355 {
  const Data355({
    required this.branchId,
    required this.date,
    required this.entries,
    required this.summary,
  });

  factory Data355.fromJson(Map<String, Object?> json) =>
      _$Data355FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String date;
  final List<Entries13> entries;
  final Summary14 summary;

  Map<String, Object?> toJson() => _$Data355ToJson(this);
}
