// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'entries5.dart';
import 'summary7.dart';

part 'data251.g.dart';

@JsonSerializable()
class Data251 {
  const Data251({
    required this.branchId,
    required this.date,
    required this.entries,
    required this.summary,
  });

  factory Data251.fromJson(Map<String, Object?> json) =>
      _$Data251FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String date;
  final List<Entries5> entries;
  final Summary7 summary;

  Map<String, Object?> toJson() => _$Data251ToJson(this);
}
