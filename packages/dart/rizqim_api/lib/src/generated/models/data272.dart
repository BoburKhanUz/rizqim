// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'entries5.dart';
import 'summary8.dart';

part 'data272.g.dart';

@JsonSerializable()
class Data272 {
  const Data272({
    required this.branchId,
    required this.date,
    required this.entries,
    required this.summary,
  });

  factory Data272.fromJson(Map<String, Object?> json) =>
      _$Data272FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String date;
  final List<Entries5> entries;
  final Summary8 summary;

  Map<String, Object?> toJson() => _$Data272ToJson(this);
}
