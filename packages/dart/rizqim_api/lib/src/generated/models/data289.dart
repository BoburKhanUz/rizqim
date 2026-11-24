// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account3.dart';
import 'entry3.dart';

part 'data289.g.dart';

@JsonSerializable()
class Data289 {
  const Data289({required this.account, required this.entry});

  factory Data289.fromJson(Map<String, Object?> json) =>
      _$Data289FromJson(json);

  final Account3 account;
  final Entry3 entry;

  Map<String, Object?> toJson() => _$Data289ToJson(this);
}
