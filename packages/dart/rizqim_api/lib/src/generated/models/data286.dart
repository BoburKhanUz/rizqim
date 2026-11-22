// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account2.dart';
import 'entries10.dart';

part 'data286.g.dart';

@JsonSerializable()
class Data286 {
  const Data286({required this.account, required this.entries});

  factory Data286.fromJson(Map<String, Object?> json) =>
      _$Data286FromJson(json);

  final Account2 account;
  final List<Entries10> entries;

  Map<String, Object?> toJson() => _$Data286ToJson(this);
}
